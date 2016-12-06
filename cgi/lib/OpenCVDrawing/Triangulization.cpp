/*
Alexandria Tran Le
Jason Edward Springer
Maxim Tiourin
Gordon Zhang
*/

#include <stdio.h>
#include <sstream>
#include <opencv2/opencv.hpp>

using namespace cv;
using namespace std;

int main(int argc, char** argv )
{
	/*
	#1.) relative filepath to frame image
	#2.) relative filepath to output to (This is not just the output directory, but also the image filename to output as, so that you don't have to worry about knowing the naming scheme)
	#3.) left eye x coordinate, or -1 if left eye not found
	#4.) left eye y coordinate, or -1 if left eye not found
	#5.) right eye x coordinate, or -1 if right eye not found
	#6.) right eye y coordinate, or -1 if right eye not found
	#7.) integer describing amount of facial data points being passed (for our purposes it will be either 0 or 68), if 0 is passed you program doesnt look for anymore arguments because it knows it wont be drawing facial data 		triangulation , if 68 is passed it should know to look for 136 numbers denoting 68 pairs of x,y coordinates
	#8-143.) coordinates for either x or y, so if we only had 3 facial data points as an example, their 3 coordinate pairs would be [x1, y1],[x2, y2],[x3, y3], then the input arguments following would be "x1 y1 x2 y2 x3 y3" 
	*/

	//Check for essential 7 arguments
   if ( argc < 8 ) {
	   //Don't have the essential 7 arguments supplied
      printf("error");
      return -1;
   }

	//Check for (x * 2) additional arguments
	istringstream spp(argv[7]);
	int pointPairs;
	if (!(spp >> pointPairs)) {
		printf("error");
		return -1;
	}
	else {
      if (argc < (8 + (pointPairs * 2))) {
         //Not enough additional arguments
         printf("error");
		   return -1;
      }
	}
	
	/* Grab pupil data */
   istringstream lxp(argv[3]);
	int pupil_lx;
	if (!(lxp >> pupil_lx)) {
		printf("error");
		return -1;
	}
	istringstream lyp(argv[4]);
	int pupil_ly;
	if (!(lyp >> pupil_ly)) {
		printf("error");
		return -1;
	}
	istringstream rxp(argv[5]);
	int pupil_rx;
	if (!(rxp >> pupil_rx)) {
		printf("error");
		return -1;
	}
	istringstream ryp(argv[6]);
	int pupil_ry;
	if (!(ryp >> pupil_ry)) {
		printf("error");
		return -1;
	}
	

   Mat image;
   image = imread(argv[1], 1);

   if (!image.data) {
      //Image could not be read
      printf("error");
      return -1;
   }
   
   //Establish color scalars
   Scalar triangleColor(255, 0, 0), pointColor(0, 255, 0); //blue, green [for some reason scalars are (BLUE, GREEN, RED)]
   
   
   //Determine width and height for bounding box
   Size size = image.size();
   Rect rect(0, 0, size.width, size.height);
   
   /* Do triangulation */
   //Collect all facial points and add them to container
   if (pointPairs > 0) {
      vector<Point2f> points;
      
      for (size_t i = 0; i < pointPairs * 2; i += 2) {
         istringstream sx(argv[8 + i]);
         float x;
         if (!(sx >> x)) {
            printf("error");
            return -1;
         }
         istringstream sy(argv[8 + i + 1]);
         float y;
         if (!(sy >> y)) {
            printf("error");
            return -1;
         }
         
         points.push_back(Point2f(x, y));
      }
      
      //Triangulate
      Subdiv2D subdiv(rect);
      
      for (size_t i = 0; i < pointPairs; i++) {
         subdiv.insert(points[i]);
      }
      
      vector<Vec6f> triangles;
      subdiv.getTriangleList(triangles);
      
      //Draw triangulation
      vector<Point> pt(3);
      
      for (size_t i = 0; i < triangles.size(); i++) {
         Vec6f tri = triangles[i];
         pt[0] = Point(cvRound(tri[0]), cvRound(tri[1]));
         pt[1] = Point(cvRound(tri[2]), cvRound(tri[3]));
         pt[2] = Point(cvRound(tri[4]), cvRound(tri[5]));
         
         if (rect.contains(pt[0]) && rect.contains(pt[1]) && rect.contains(pt[2])) {
            line(image, pt[0], pt[1], triangleColor, 1, CV_AA, 0);
            line(image, pt[1], pt[2], triangleColor, 1, CV_AA, 0);
            line(image, pt[2], pt[0], triangleColor, 1, CV_AA, 0);
         }
      }
   }
   
   //Draw pupils if they exist
   if (pupil_lx != -1 && pupil_ly != -1) {
      Point pupil_left = Point(pupil_lx, pupil_ly);
      
      if (rect.contains(pupil_left)) {
         circle(image, pupil_left, 4, pointColor);
      }
   }
   if (pupil_rx != -1 && pupil_ry != -1) {
      Point pupil_right = Point(pupil_rx, pupil_ry);
      
      if (rect.contains(pupil_right)) {
         circle(image, pupil_right, 4, pointColor);
      }
   }
   
   //Write image
   if (!imwrite(argv[2], image)) {
      //Error while writing image
      printf("error");
      return -1;
   }

   printf("success");

   return 0;
}
